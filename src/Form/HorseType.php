<?php

namespace App\Form;

use App\Entity\Horse;
use App\Enum\BlanketWeight;
use App\Enum\Clip;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class HorseType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, ['label' => 'Nom'])
            ->add('breed', TextType::class, ['label' => 'Race', 'required' => false])
            ->add('birthYear', IntegerType::class, ['label' => 'Année de naissance', 'required' => false])
            ->add('clip', EnumType::class, [
                'class' => Clip::class,
                'label' => 'Tonte',
                'choice_label' => static fn (Clip $clip) => $clip->label(),
            ])
            ->add('currentBlanket', EnumType::class, [
                'class' => BlanketWeight::class,
                'label' => 'Couverture portée actuellement',
                'choice_label' => static fn (BlanketWeight $weight) => $weight->label(),
                'help' => 'Sert à comparer le microclimat mesuré au conseil de couverture.',
            ])
            ->add('coldSensitive', CheckboxType::class, [
                'label' => 'Cheval frileux ou maigre',
                'required' => false,
            ])
            ->add('hasShelter', CheckboxType::class, [
                'label' => 'Abri disponible au pré',
                'required' => false,
            ])
            ->add('paddock', HiddenType::class, [
                'label' => false,
                'required' => false,
                // Un champ caché remonte ses erreurs au formulaire par défaut : on les garde sous la carte.
                'error_bubbling' => false,
            ]);

        // Le polygone de l'enclos transite en JSON entre la carte et le serveur.
        $builder->get('paddock')->addModelTransformer(new CallbackTransformer(
            static fn (?array $points): string => json_encode($points ?? []),
            static function (?string $json): array {
                $points = json_decode($json ?: '[]', true);
                if (!\is_array($points)) {
                    return [];
                }

                return array_values(array_map(
                    static fn ($point) => [(float) ($point[0] ?? 0), (float) ($point[1] ?? 0)],
                    array_filter($points, \is_array(...)),
                ));
            },
        ));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Horse::class,
            // Libellés déjà rédigés en français : pas de passage par le traducteur.
            'translation_domain' => false,
        ]);
    }
}
